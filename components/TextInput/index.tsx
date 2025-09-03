import React, { useState } from 'react';
import { Text, TextInput, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';


interface InputProps {  
  label : string;
  placeholder?: string;
  note?: string;
}

export function Input({label, placeholder,note}: InputProps){
  const [text, setText] = useState('');
  return (
    <View>
      <Text className="text-2xl dark:color-white">{label}</Text>
      <SafeAreaProvider>
        <SafeAreaView>
          <TextInput value={text} placeholder={placeholder} onChangeText={setText} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>
        </SafeAreaView>
      </SafeAreaProvider>
      <Text className="dark:color-white mt-0">{note}</Text>
    </View>
  );
}

export function PasswordInput({label, placeholder,note}: InputProps){
  const [password, setPassword] = useState('');
  return (
    <View>
        <Text className="text-2xl dark:color-white">{label}</Text>
        <SafeAreaProvider>
          <SafeAreaView>
            <TextInput secureTextEntry={true} value={password} placeholder={placeholder} onChangeText={setPassword} className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"/>
          </SafeAreaView>
        </SafeAreaProvider>
        <Text className="dark:color-white mt-0">{note}</Text>
    </View>
  );
}
