import "../../../../style/global.css";

import { Link, useRouter } from "expo-router";
import React from "react";
import { authCheck } from "@/app/authCheck/authCheck";
import {
  Image,
  Pressable,
  SafeAreaView,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";

export default function Signup() {

  authCheck(); 

  const [nome, setNome] = React.useState("");
  const [email, setEmail] = React.useState("");
  const [senha, setSenha] = React.useState("");
  const [c_senha, setCSenha] = React.useState("");

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertMessage, setAlertMessage] = React.useState("");
  const [alertTitle, setAlertTitle] = React.useState("Ocorreu um erro");

  const router = useRouter();

  function showError(message: string, title = "Ocorreu um erro") {
    setAlertTitle(title);
    setAlertMessage(message);
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
      const res = await fetch("http://192.168.1.9/SICAD/cadastro.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nome, email, senha }), // c_senha não é usada no PHP
      });

      // Se o PHP retornar HTML/erro, isso evita crash no .json()
      const raw = await res.text();
      let data: any;
      try {
        data = JSON.parse(raw);
      } catch {
        showError("Resposta inválida do servidor (não veio JSON).");
        console.log("Resposta bruta do servidor:", raw);
        return;
      }

      console.log("Resposta do backend:", data);

      if (data?.success) {
        router.push("/(tabs)/(painel)/home/page");
      } else {
        showError(data?.message ?? "Erro ao cadastrar usuário");
      }
    } catch (error: any) {
      console.error("Erro na requisição:", error);
      showError(error?.message ?? "Erro na requisição");
    }
  }

  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className="w-5/12">
        <Image
          source={require("../../../../assets/images/side-view-login-cadastro.png")}
          style={{ width: "100%" }}
        />
      </View>

      <View className="flex-1 items-center justify-center">
        <ScrollView>
          <View>
            <Image
              source={require("../../../../assets/images/logo-composta.png")}
              className="mb-4"
            />
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
                    <Text className="text-2xl dark:color-white">
                      Nome Completo
                    </Text>
                    <TextInput
                      value={nome}
                      onChangeText={setNome}
                      className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                    />
                    <Text className="dark:color-white mt-0">
                      Este nome sera utilizado em todos os documentos da
                      plataforma
                    </Text>
                  </View>

                  <View>
                    <Text className="text-2xl dark:color-white">E-mail</Text>
                    <TextInput
                      value={email}
                      onChangeText={setEmail}
                      autoCapitalize="none"
                      keyboardType="email-address"
                      className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                    />
                  </View>

                  <View>
                    <Text className="text-2xl dark:color-white">Senha</Text>
                    <TextInput
                      secureTextEntry
                      value={senha}
                      onChangeText={setSenha}
                      className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                    />
                  </View>

                  <View>
                    <Text className="text-2xl dark:color-white">
                      Confirmar Senha
                    </Text>
                    <TextInput
                      secureTextEntry
                      value={c_senha}
                      onChangeText={setCSenha}
                      className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                    />
                  </View>

                  <View className="w-full rounded-xl bg-slate-200 dark:bg-slate-800 mt-4 px-2 py-2">
                    <Text className="dark:color-white">
                      Ao criar uma conta, você concorda com os{" "}
                      <Link href={"/"} className="dark:color-white underline">
                        Termos de Serviço
                      </Link>{" "}
                      e a{" "}
                      <Link href={"/"} className="dark:color-white underline">
                        Política de Privacidade
                      </Link>{" "}
                      da plataforma.
                    </Text>
                  </View>

                  <Pressable
                    onPress={handleSignUp}
                    className="w-full h-12 rounded-xl bg-green-600 mt-4 justify-center items-center"
                  >
                    <Text className="text-2xl font-bold color-white">
                      Criar conta
                    </Text>
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
        onClose={() => setAlertVisible(false)}
      />
    </View>
  );
}
