import "../../../../style/global.css";

import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";
import { Link, useRouter } from "expo-router";
import React from "react";
import { Image, Pressable, SafeAreaView, ScrollView, Text, TextInput, View } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";



export default function Signup() {
  const [nome, setNome] = React.useState("");
  const [email, setEmail] = React.useState("");
  const [senha, setSenha] = React.useState("");
  const [c_senha, setCSenha] = React.useState("");

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertMessage, setAlertMessage] = React.useState("");
  const [alertTitle, setAlertTitle] = React.useState("Ocorreu um erro");
  const [alertVariant, setAlertVariant] = React.useState<"error" | "info" | "success">("error");

  // modo verificação
  const [needVerify, setNeedVerify] = React.useState(false);
  const [token, setToken] = React.useState("");

  const router = useRouter();

  function showError(message: string, title = "Ocorreu um erro") {
    setAlertVariant("error");
    setAlertTitle(title);
    setAlertMessage(message);
    setNeedVerify(false);
    setAlertVisible(true);
  }

  function showVerify(message: string) {
    setAlertVariant("info");
    setAlertTitle("Verifique seu e-mail");
    setAlertMessage(message);
    setNeedVerify(true);
    setAlertVisible(true);
  }

  async function handleSignUp() {
    if (!nome || !email || !senha || !c_senha) {
      showError("Por favor, preencha todos os campos");
      return;
    }
    if (senha !== c_senha) {
      showError("As senhas não coincidem");
      return;
    }

    try {
      const res = await fetch("http://192.168.2.110/SICAD/cadastro.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nome, email, senha }),
      });

      const raw = await res.text();
      let data: any;
      try {
        data = JSON.parse(raw);
      } catch {
        showError("Resposta inválida do servidor (não veio JSON).");
        console.log("Resposta bruta do servidor:", raw);
        return;
      }

      if (data?.success) {
        // aqui ao invés de ir pro home, pede token
        setToken("");
        showVerify(data?.message ?? "Enviamos um token para seu e-mail. Cole ele aqui para ativar sua conta.");
      } else {
        showError(data?.message ?? "Erro ao cadastrar usuário");
      }
    } catch (error: any) {
      console.error("Erro na requisição:", error);
      showError(error?.message ?? "Erro na requisição");
    }
  }

  async function handleVerifyEmail() {
    const t = token.trim();
    if (!t) return;

    try {
      const res = await fetch("http://192.168.2.110/SICAD/verify_email.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, token: t }),
      });

      const raw = await res.text();
      let data: any;
      try {
        data = JSON.parse(raw);
      } catch {
        setAlertVariant("error");
        setAlertTitle("Erro");
        setAlertMessage("Resposta inválida do servidor.");
        setNeedVerify(true);
        setAlertVisible(true);
        return;
      }

      if (data?.success) {
        // ✅ opção 1 (melhor UX): logar automaticamente após validar
        const loginResp = await axios.post("http://192.168.2.110/SICAD/login.php", { email, senha });
        if (loginResp.data?.success) {
          const tokenJwt = loginResp.data.token;
          const nomeUser = loginResp.data.usuario.nome;
          const id = loginResp.data.usuario.id;

          await AsyncStorage.setItem("userToken", tokenJwt);
          await AsyncStorage.setItem("userName", nomeUser);
          await AsyncStorage.setItem("userId", String(id));

          setAlertVisible(false);
          router.push("/(tabs)/(painel)/home/page");
          return;
        }

        // fallback: manda pra login
        setAlertVariant("success");
        setAlertTitle("Sucesso");
        setAlertMessage("E-mail verificado! Agora faça login.");
        setNeedVerify(false);
        setAlertVisible(true);

        router.push("/(tabs)/(auth)/signin/page");
      } else {
        // mantém no modo verify, só muda pra “erro” e mostra msg
        setAlertVariant("error");
        setAlertTitle("Token inválido");
        setAlertMessage(data?.message ?? "Token inválido ou expirado.");
        setNeedVerify(true);
        setAlertVisible(true);
      }
    } catch (e) {
      console.error(e);
      setAlertVariant("error");
      setAlertTitle("Erro de conexão");
      setAlertMessage("Não foi possível validar agora. Tente novamente.");
      setNeedVerify(true);
      setAlertVisible(true);
    }
  }

  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className="w-5/12">
        <Image source={require("../../../../assets/images/side-view-login-cadastro.png")} style={{ width: "100%" }} />
      </View>

      <View className="flex-1 items-center justify-center">
        <ScrollView>
          <View>
            <Image source={require("../../../../assets/images/logo-composta.png")} className="mb-4" />
            <Text className="text-6xl dark:color-white">Crie sua conta</Text>

            <Text className="text-2xl dark:color-white">
              Ja tem uma conta ?{" "}
              <Link href={"../signin/page"} className="text-2xl color-sky-500">
                clique aqui para fazer login
              </Link>
            </Text>

            <SafeAreaProvider>
              <SafeAreaView>
                <View className="flex flex-col gap-1 mt-4">
                  <View>
                    <Text className="text-2xl dark:color-white">Nome Completo</Text>
                    <TextInput value={nome} onChangeText={setNome} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4" />
                  </View>

                  <View>
                    <Text className="text-2xl dark:color-white">E-mail</Text>
                    <TextInput value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address"
                      className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4" />
                  </View>

                  <View>
                    <Text className="text-2xl dark:color-white">Senha</Text>
                    <TextInput secureTextEntry value={senha} onChangeText={setSenha}
                      className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4" />
                  </View>

                  <View>
                    <Text className="text-2xl dark:color-white">Confirmar Senha</Text>
                    <TextInput secureTextEntry value={c_senha} onChangeText={setCSenha}
                      className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4" />
                  </View>

                  <Pressable onPress={handleSignUp} className="w-full h-12 rounded-xl bg-green-600 mt-4 justify-center items-center">
                    <Text className="text-2xl font-bold color-white">Criar conta</Text>
                  </Pressable>
                </View>
              </SafeAreaView>
            </SafeAreaProvider>
          </View>
        </ScrollView>
      </View>

      <NiceAlert
        visible={alertVisible}
        title={alertTitle}
        message={alertMessage}
        variant={alertVariant}
        onClose={() => setAlertVisible(false)}
        showInput={needVerify}
        inputPlaceholder="Cole o token aqui"
        inputValue={token}
        onChangeInput={setToken}
        confirmText="Validar"
        onConfirm={needVerify ? handleVerifyEmail : undefined}
        confirmDisabled={needVerify ? token.trim().length === 0 : false}
      />
    </View>
  );
}