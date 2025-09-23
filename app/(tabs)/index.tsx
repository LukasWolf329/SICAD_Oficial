import "../../style/global.css";

import { Link, router } from "expo-router";
import React from 'react';
import { Image, Pressable, Text, View } from 'react-native';

export default function Index() {
  return (
    <View className="flex-1 bg-white dark:bg-[#121212] justify-center items-center p-8">
      <Image
        source={require("../../assets/images/favicon.png")}
        style={{ width: 100, height: 100, marginBottom: 16 }}
        resizeMode="contain"
      />
      <Text className="text-3xl font-bold mb-2 dark:color-white">
        Bem-vindo ao SICAD
      </Text>

      {/* Botão Entrar */}
      <Link href="/(tabs)/(auth)/signin/page" className="w-full">
        <Pressable className="w-full bg-[#059212] rounded-xl px-4 py-3 mb-4 text-center">
          <Text className="text-white text-lg font-semibold">Entrar</Text>
        </Pressable>
      </Link>

      {/* Botão Criar Conta */}
      <Link href="/(tabs)/(auth)/signup/page" className="w-full">
        <Pressable className="w-full border border-gray-300 dark:border-gray-500 rounded-xl px-4 py-3 text-center">
          <Text className="text-lg font-semibold dark:color-white">Criar Conta</Text>
        </Pressable>
      </Link>

    </View>

    
  );
}
